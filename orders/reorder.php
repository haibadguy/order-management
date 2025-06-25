<?php
require_once '../includes/auth.php';
checkPageAccess('add');

$errors = [];

try {
    // Kiểm tra ID đơn hàng
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        setMessage('ID đơn hàng không hợp lệ', 'danger');
        header('Location: index.php');
        exit();
    }

    // Lấy thông tin đơn hàng cũ
    $stmt = mysqli_prepare($conn, '
        SELECT o.*, c.id as customer_id, c.full_name as customer_name,
               sd.shipping_address, sd.shipping_method, sd.shipping_fee,
               sd.province_code, sd.district_code, sd.ward_code
        FROM `order` o
        JOIN customer c ON o.customer_id = c.id
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

    // Lấy chi tiết sản phẩm từ đơn hàng cũ
    $stmt = mysqli_prepare($conn, '
        SELECT oi.*, p.id as product_id, p.name as product_name, 
               p.price as current_price, p.stock as current_stock,
               c.name as category_name
        FROM order_item oi
        JOIN product p ON oi.product_id = p.id
        LEFT JOIN category c ON p.category_id = c.id
        WHERE oi.order_id = ?
    ');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order_items = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Lấy danh sách khách hàng
    $result = mysqli_query($conn, 'SELECT id, full_name, phone FROM customer ORDER BY full_name');
    $customers = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Lấy danh sách sản phẩm
    $result = mysqli_query($conn, '
        SELECT p.*, c.name as category_name 
        FROM product p 
        JOIN category c ON p.category_id = c.id 
        WHERE p.is_active = 1 
        ORDER BY p.name
    ');
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Parse địa chỉ cũ
    $old_address = $order['shipping_address'];
    $address_parts = array_reverse(explode(', ', $old_address));
    $province_name = $address_parts[0] ?? '';
    $district_name = $address_parts[1] ?? '';
    $ward_name = $address_parts[2] ?? '';
    $street = implode(', ', array_reverse(array_slice($address_parts, 3)));

    // Lấy mã code từ shipping_details
    $province_code = $order['province_code'] ?? '';
    $district_code = $order['district_code'] ?? '';
    $ward_code = $order['ward_code'] ?? '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors['csrf'] = 'CSRF token không hợp lệ';
    }

    $customer_id = intval($_POST['customer_id'] ?? 0);
    $employee_id = $_SESSION['user_id'];
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $shipping_method = trim($_POST['shipping_method'] ?? 'standard');
    $shipping_status = trim($_POST['shipping_status'] ?? 'pending');
    $shipping_fee = floatval($_POST['shipping_fee'] ?? 30000);
    $payment_method = trim($_POST['payment_method'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $products_data = $_POST['products'] ?? [];
    $discount_percentage = floatval($_POST['discount_percentage'] ?? 0);
    $tax_percentage = floatval($_POST['tax_percentage'] ?? 1);
    $estimated_delivery_date = trim($_POST['estimated_delivery_date'] ?? '');

    // Nếu không có ngày giao dự kiến, set mặc định là 3 ngày từ hiện tại
    if (empty($estimated_delivery_date)) {
        $estimated_delivery_date = date('Y-m-d H:i:s', strtotime('+3 days'));
    } else {
        // Chuyển đổi từ datetime-local về định dạng MySQL
        $estimated_delivery_date = date('Y-m-d H:i:s', strtotime($estimated_delivery_date));
    }

    if (!$customer_id) {
        $errors['customer_id'] = 'Vui lòng chọn khách hàng';
    }

    if (empty($shipping_address)) {
        $errors['shipping_address'] = 'Vui lòng nhập địa chỉ giao hàng';
    }

    if (empty($shipping_method)) {
        $errors['shipping_method'] = 'Vui lòng chọn phương thức vận chuyển';
    }

    if (empty($payment_method)) {
        $errors['payment_method'] = 'Vui lòng chọn phương thức thanh toán';
    }

    if (empty($products_data)) {
        $errors['products'] = 'Vui lòng chọn ít nhất một sản phẩm';
    }

    if (empty($errors)) {
        try {
            mysqli_begin_transaction($conn);

            // Tính tổng tiền
            $subtotal = 0;
            foreach ($products_data as $product) {
                $subtotal += floatval($product['price']) * intval($product['quantity']);
            }

            $tax_amount = round($subtotal * ($tax_percentage / 100)); // Tính thuế theo %
            $total_amount = $subtotal + $tax_amount + $shipping_fee;

            // Tạo đơn hàng mới
            $stmt = mysqli_prepare($conn, '
                INSERT INTO `order` (
                    customer_id, employee_id,
                    payment_method, payment_status, status,
                    subtotal, tax_amount, shipping_fee, total_amount,
                    discount_percentage, notes,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ');

            $status = 'pending';
            $payment_status = ($payment_method === 'cod') ? 'pending' : 'pending';
            
            mysqli_stmt_bind_param($stmt, 'iisssddddss',
                $customer_id, $employee_id,
                $payment_method, $payment_status, $status,
                $subtotal, $tax_amount, $shipping_fee, $total_amount,
                $discount_percentage, $notes
            );
            mysqli_stmt_execute($stmt);
            $new_order_id = mysqli_insert_id($conn);

            // Thêm thông tin vận chuyển
            $stmt = mysqli_prepare($conn, '
                INSERT INTO shipping_details (
                    order_id, shipping_address, shipping_method,
                    shipping_status, shipping_fee, estimated_delivery_date,
                    province_code, district_code, ward_code
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            mysqli_stmt_bind_param($stmt, 'isssdssss',
                $new_order_id, $shipping_address, $shipping_method,
                $shipping_status, $shipping_fee, $estimated_delivery_date,
                $province_code, $district_code, $ward_code
            );
            mysqli_stmt_execute($stmt);

            // Thêm chi tiết đơn hàng
            $stmt = mysqli_prepare($conn, '
                INSERT INTO order_item (order_id, product_id, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ');

            foreach ($products_data as $product) {
                $product_id = intval($product['id']);
                $quantity = intval($product['quantity']);
                $price = floatval($product['price']);

                // Kiểm tra số lượng tồn kho
                $check = mysqli_prepare($conn, 'SELECT stock FROM product WHERE id = ?');
                mysqli_stmt_bind_param($check, 'i', $product_id);
                mysqli_stmt_execute($check);
                $result = mysqli_stmt_get_result($check);
                $row = mysqli_fetch_assoc($result);
                $stock = $row['stock'];

                if ($quantity > $stock) {
                    throw new Exception("Sản phẩm ID $product_id không đủ số lượng trong kho");
                }

                // Thêm chi tiết đơn hàng
                mysqli_stmt_bind_param($stmt, 'iiid', 
                    $new_order_id, $product_id, $quantity, $price
                );
                mysqli_stmt_execute($stmt);

                // Cập nhật số lượng tồn kho
                $update = mysqli_prepare($conn, 'UPDATE product SET stock = stock - ? WHERE id = ?');
                mysqli_stmt_bind_param($update, 'ii', $quantity, $product_id);
                mysqli_stmt_execute($update);
            }

            mysqli_commit($conn);
            setMessage('Tạo đơn hàng thành công');
            header('Location: index.php');
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors['db'] = 'Lỗi khi tạo đơn hàng: ' . $e->getMessage();
        }
    }
}

// Thiết lập tiêu đề trang
$page_title = "Mua Lại Đơn Hàng #" . $order['id'];
require_once '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Mua Lại Đơn Hàng #<?php echo $order['id']; ?></h2>
            <p class="text-muted mb-0">Khách hàng: <?php echo htmlspecialchars($order['customer_name']); ?></p>
        </div>
        <div>
            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Quay Lại
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Form thêm mới -->
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
                                            <?php echo $customer['id'] == $order['customer_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['full_name'] . ' - ' . $customer['phone']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Ghi Chú</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($order['notes']); ?></textarea>
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
                                <?php
                                $paymentMethods = [
                                    'cod' => 'Thanh toán khi nhận hàng (COD)',
                                    'bank_transfer' => 'Chuyển khoản ngân hàng',
                                    'momo' => 'Ví MoMo',
                                    'zalopay' => 'ZaloPay'
                                ];
                                foreach ($paymentMethods as $value => $label):
                                ?>
                                    <option value="<?php echo $value; ?>" 
                                            <?php echo $value === $order['payment_method'] ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
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
                                    <label class="form-label">Thời Gian Giao Dự Kiến</label>
                                    <input type="datetime-local" name="estimated_delivery_date" class="form-control" 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime('+3 days')); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Phí Vận Chuyển</label>
                                    <input type="number" name="shipping_fee" class="form-control" 
                                           value="30000" min="0" required>
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
                                                    <?php echo $product['id'] == $item['product_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($product['name'] . ' - ' . $product['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="form-control price-input" 
                                           name="products[<?php echo $index; ?>][price]" 
                                           value="<?php echo $item['current_price']; ?>" min="0" readonly>
                                </td>
                                <td>
                                    <input type="number" class="form-control quantity-input" 
                                           name="products[<?php echo $index; ?>][quantity]" 
                                           value="<?php echo $item['quantity']; ?>" min="1" required>
                                </td>
                                <td class="row-total">0đ</td>
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
                                <td id="totalAmount" colspan="2">0đ</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-end mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-shopping-cart"></i> Tạo Đơn Hàng Mới
            </button>
        </div>
    </form>
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
        rowCount++;
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

    // Điền thông tin địa chỉ cũ
    const oldProvince = document.getElementById('old_province').value;
    const oldDistrict = document.getElementById('old_district').value;
    const oldWard = document.getElementById('old_ward').value;
    const oldStreet = document.getElementById('old_street').value;
    const oldProvinceCode = document.getElementById('old_province_code').value;
    const oldDistrictCode = document.getElementById('old_district_code').value;
    const oldWardCode = document.getElementById('old_ward_code').value;

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

    // Set giá trị địa chỉ cũ nếu có
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

    // Khởi tạo tổng tiền
    updateTotals();
});
</script>

<?php require_once '../includes/footer.php'; ?> 