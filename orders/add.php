<?php
require_once '../includes/auth.php';

$errors = [];

try {
    // Lấy danh sách khách hàng
    $result = mysqli_query($conn, 'SELECT id, full_name, phone FROM customer ORDER BY full_name');
    $customers = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Kiểm tra nếu là mua lại từ đơn hàng cũ
    $reorder_data = $_SESSION['reorder_data'] ?? null;
    $is_reorder = isset($_GET['reorder']) && isset($_GET['id']) && $reorder_data;
    $old_order_id = intval($_GET['id'] ?? 0);

    if ($is_reorder && $old_order_id != $reorder_data['old_order_id']) {
        setMessage('Dữ liệu đơn hàng không hợp lệ', 'danger');
        header('Location: view.php?id=' . $old_order_id);
        exit();
    }

    // Lấy danh sách sản phẩm
    $product_query = 'SELECT p.*, c.name as category_name 
                     FROM product p 
                     JOIN category c ON p.category_id = c.id 
                     WHERE p.is_active = 1' . 
                     (!$is_reorder ? ' AND p.stock > 0' : '') . 
                     ' ORDER BY p.name';
    $result = mysqli_query($conn, $product_query);
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC);

    if ($is_reorder) {
        $customer_id = $reorder_data['customer_id'];
        $shipping_address = $reorder_data['shipping_address'];
        $shipping_method = $reorder_data['shipping_method'];
        $payment_method = $reorder_data['payment_method'];
        $notes = $reorder_data['notes'];
        $selected_products = $reorder_data['items'];
        unset($_SESSION['reorder_data']); // Xóa dữ liệu sau khi sử dụng
    }
} catch (Exception $e) {
    setMessage('Lỗi khi lấy dữ liệu: ' . $e->getMessage(), 'danger');
    header('Location: index.php');
    exit();
}

function getCustomerAddresses($conn, $customer_id) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM customer_address WHERE customer_id = ? ORDER BY is_default DESC');
    mysqli_stmt_bind_param($stmt, 'i', $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors['csrf'] = 'CSRF token không hợp lệ';
    }

    $customer_id = intval($_POST['customer_id'] ?? 0);
    $employee_id = $_SESSION['user_id'];
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $shipping_method = trim($_POST['shipping_method'] ?? 'standard');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $products_data = $_POST['products'] ?? [];
    $discount_percentage = floatval($_POST['discount_percentage'] ?? 0);
    
    // Lấy mã tỉnh/huyện/xã và địa chỉ đường
    $province_code = trim($_POST['province_code'] ?? '');
    $district_code = trim($_POST['district_code'] ?? '');
    $ward_code = trim($_POST['ward_code'] ?? '');
    $street = trim($_POST['street'] ?? '');

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

            $subtotal = 0;
            foreach ($products_data as $product) {
                $subtotal += round(floatval($product['price']) * intval($product['quantity']));
            }

            $tax_amount = round($subtotal * 0.01); // 1% VAT
            $shipping_fee = 30000;
            $total_amount = $subtotal + $tax_amount + $shipping_fee - $discount_percentage*$subtotal/100;

            $stmt = mysqli_prepare($conn, '
                INSERT INTO `order` (
                    customer_id, employee_id, 
                    subtotal, tax_amount, shipping_fee,
                    discount_percentage, total_amount,
                    payment_method, notes,
                    status, payment_status
                ) VALUES (
                    ?, ?, 
                    ?, ?, ?,
                    ?, ?,
                    ?, ?,
                    "pending", "pending"
                )
            ');
            mysqli_stmt_bind_param($stmt, 'iidddddss', 
                $customer_id, $employee_id,
                $subtotal, $tax_amount, $shipping_fee,
                $discount_percentage, $total_amount,
                $payment_method, $notes
            );
            mysqli_stmt_execute($stmt);
            $order_id = mysqli_insert_id($conn);

            // Thêm thông tin giao hàng
            $estimated_delivery_date = date('Y-m-d H:i:s', strtotime('+3 days')); // Mặc định 3 ngày
            $shipping_status = 'pending'; // Trạng thái mặc định
            $stmt = mysqli_prepare($conn, '
                INSERT INTO shipping_details (
                    order_id, shipping_address, shipping_method,
                    shipping_status, shipping_fee, estimated_delivery_date,
                    province_code, district_code, ward_code, street_address
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            mysqli_stmt_bind_param($stmt, 'isssdsssss',
                $order_id, $shipping_address, $shipping_method,
                $shipping_status, $shipping_fee, $estimated_delivery_date,
                $province_code, $district_code, $ward_code, $street
            );
            mysqli_stmt_execute($stmt);

            // Thêm chi tiết đơn hàng
            $stmt = mysqli_prepare($conn, '
                INSERT INTO order_item (
                    order_id, product_id, quantity, 
                    unit_price
                ) VALUES (?, ?, ?, ?)
            ');

            foreach ($products_data as $product) {
                $product_id = intval($product['id']);
                $quantity = intval($product['quantity']);
                $price = floatval($product['price']);

                mysqli_stmt_bind_param($stmt, 'iiid', 
                    $order_id, $product_id, $quantity, $price
                );
                mysqli_stmt_execute($stmt);

                // Cập nhật số lượng tồn kho
                $update_stock = mysqli_prepare($conn, '
                    UPDATE product 
                    SET stock = stock - ? 
                    WHERE id = ?
                ');
                mysqli_stmt_bind_param($update_stock, 'ii', $quantity, $product_id);
                mysqli_stmt_execute($update_stock);
            }

            mysqli_commit($conn);
            setMessage('Thêm đơn hàng thành công', 'success');
            header('Location: view.php?id=' . $order_id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors['db'] = 'Lỗi khi thêm đơn hàng: ' . $e->getMessage();
        }
    }
} else if ($is_reorder) {
    // Nếu là reorder qua GET, tạo POST request mới
    ?>
    <form id="reorderForm" method="POST" action="add.php?reorder=1">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
        <input type="hidden" name="shipping_address" value="<?php echo htmlspecialchars($shipping_address); ?>">
        <input type="hidden" name="shipping_method" value="<?php echo htmlspecialchars($shipping_method); ?>">
        <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($payment_method); ?>">
        <input type="hidden" name="notes" value="<?php echo htmlspecialchars($notes); ?>">
        
        <?php foreach ($selected_products as $index => $item): ?>
        <input type="hidden" name="products[<?php echo $index; ?>][id]" value="<?php echo $item['product_id']; ?>">
        <input type="hidden" name="products[<?php echo $index; ?>][quantity]" value="<?php echo $item['quantity']; ?>">
        <input type="hidden" name="products[<?php echo $index; ?>][price]" value="<?php echo $item['unit_price']; ?>">
        <?php endforeach; ?>
    </form>
    <div class="text-center my-5">
        <h3>Đang xử lý...</h3>
        <p>Vui lòng đợi trong giây lát.</p>
    </div>
    <script>
        window.onload = function() {
            document.getElementById('reorderForm').submit();
        };
    </script>
    <?php
    exit();
}

// Thiết lập tiêu đề trang
$page_title = "Tạo Đơn Hàng";
require_once '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Tạo Đơn Hàng Mới</h2>
            <?php if ($is_reorder): ?>
            <p class="text-muted mb-0">
                Mua lại từ đơn hàng #<?php echo $reorder_data['old_order_id']; ?>
            </p>
            <?php endif; ?>
        </div>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay Lại
        </a>
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
                                            <?php echo ($is_reorder && $customer['id'] == $customer_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['full_name'] . ' - ' . $customer['phone']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Ghi Chú</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $is_reorder ? htmlspecialchars($notes) : ''; ?></textarea>
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
                                <option value="cod">Thanh toán khi nhận hàng (COD)</option>
                                <option value="bank_transfer">Chuyển khoản ngân hàng</option>
                                <option value="momo">Ví MoMo</option>
                                <option value="zalopay">ZaloPay</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Giảm Giá (%)</label>
                            <input type="number" name="discount_percentage" class="form-control" 
                                   value="0" min="0" max="100" step="0.1" required>
                            
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Thuế (%)</label>
                            <input type="number" name="tax_percentage" class="form-control" 
                                   value="1" min="0" required>
                           
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
                                        <option value="standard">Giao hàng tiêu chuẩn</option>
                                        <option value="express">Giao hàng nhanh</option>
                                        <option value="same_day">Giao hàng trong ngày</option>
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
                            <?php if ($is_reorder && !empty($selected_products)): ?>
                                <?php foreach ($selected_products as $index => $item): ?>
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
                                               value="<?php echo $item['unit_price']; ?>" min="0" required>
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
                            <?php else: ?>
                                    <tr class="product-row">
                                        <td>
                                            <select class="form-select product-select" name="products[0][id]" required>
                                                <option value="">Chọn sản phẩm</option>
                                                <?php foreach ($products as $product): ?>
                                                    <option value="<?php echo $product['id']; ?>" 
                                                            data-price="<?php echo $product['price']; ?>"
                                                        data-stock="<?php echo $product['stock']; ?>">
                                                        <?php echo htmlspecialchars($product['name'] . ' - ' . $product['category_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    <td>
                                        <input type="number" class="form-control price-input" 
                                               name="products[0][price]" value="0" min="0" required>
                                    </td>
                                        <td>
                                            <input type="number" class="form-control quantity-input" 
                                               name="products[0][quantity]" value="1" min="1" required>
                                        </td>
                                    <td class="row-total">0đ</td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm remove-row">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                            <?php endif; ?>
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
                <i class="fas fa-save"></i> Tạo Đơn Hàng
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
    let rowCount = 0;

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
</script>

<?php require_once '../includes/footer.php'; ?> 