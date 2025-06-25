<?php
require_once '../includes/auth.php';

try {
    $result = mysqli_query($conn, 'SELECT id, name FROM category WHERE is_active = 1 ORDER BY name');
    $categories = mysqli_fetch_all($result, MYSQLI_ASSOC);
} catch (Exception $e) {
    setMessage('Lỗi khi lấy danh sách danh mục: ' . $e->getMessage(), 'danger');
    header('Location: index.php');
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    validateCSRFToken($_POST['csrf_token'] ?? '');

    // Lấy dữ liệu từ form
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $stock = intval($_POST['stock'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $is_featured = isset($_POST['is_featured']);
    $is_active = isset($_POST['is_active']);
    
    // Xử lý upload ảnh
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file = $_FILES['image'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors['image'] = 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF)';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $errors['image'] = 'Kích thước file không được vượt quá 5MB';
        } else {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $upload_dir = '../uploads/products/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $image = 'uploads/products/' . $filename;
            } else {
                $errors['image'] = 'Không thể upload file. Vui lòng thử lại';
            }
        }
    }

    // Validate dữ liệu
    if (empty($name)) {
        $errors['name'] = 'Vui lòng nhập tên sản phẩm';
    }

    if ($price <= 0) {
        $errors['price'] = 'Giá bán phải lớn hơn 0';
    }

    if ($sale_price !== null && $sale_price > $price) {
        $errors['sale_price'] = 'Giá khuyến mãi phải nhỏ hơn hoặc bằng giá bán';
    }

    if ($stock < 0) {
        $errors['stock'] = 'Số lượng tồn kho không được âm';
    }

    // Nếu không có lỗi thì thêm sản phẩm mới
    if (empty($errors)) {
        try {
            $stmt = mysqli_prepare($conn, '
                INSERT INTO product (
                    name, price, sale_price, stock, description, 
                    category_id, brand, image, is_featured, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            mysqli_stmt_bind_param($stmt, 'sddisissii', 
                $name, $price, $sale_price, $stock, $description,
                $category_id, $brand, $image, $is_featured, $is_active
            );
            mysqli_stmt_execute($stmt);

            setMessage('Thêm sản phẩm thành công');
            header('Location: index.php');
            exit();
        } catch (Exception $e) {
            $errors['db'] = 'Lỗi khi thêm sản phẩm: ' . $e->getMessage();
        }
    }
}

// Thiết lập tiêu đề trang
$page_title = "Thêm Sản Phẩm";
require_once '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Thêm Sản Phẩm Mới</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay Lại
        </a>
    </div>

    <?php if (isset($errors['db'])): ?>
        <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="name" class="form-label">Tên Sản Phẩm</label>
                            <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                   id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Mô Tả</label>
                            <textarea class="form-control" id="description" name="description" rows="5"
                                    ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Danh Mục</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">Chọn danh mục</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="brand" class="form-label">Thương Hiệu</label>
                            <input type="text" class="form-control" id="brand" name="brand" 
                                   value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="price" class="form-label">Giá Bán</label>
                            <input type="number" class="form-control <?php echo isset($errors['price']) ? 'is-invalid' : ''; ?>" 
                                   id="price" name="price" min="0"
                                   value="<?php echo htmlspecialchars($_POST['price'] ?? '0'); ?>" required>
                            <?php if (isset($errors['price'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['price']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="sale_price" class="form-label">Giá Khuyến Mãi</label>
                            <input type="number" class="form-control <?php echo isset($errors['sale_price']) ? 'is-invalid' : ''; ?>" 
                                   id="sale_price" name="sale_price" min="0"
                                   value="<?php echo htmlspecialchars($_POST['sale_price'] ?? ''); ?>">
                            <?php if (isset($errors['sale_price'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['sale_price']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="stock" class="form-label">Tồn Kho</label>
                            <input type="number" class="form-control <?php echo isset($errors['stock']) ? 'is-invalid' : ''; ?>" 
                                   id="stock" name="stock" min="0" 
                                   value="<?php echo htmlspecialchars($_POST['stock'] ?? '0'); ?>" required>
                            <?php if (isset($errors['stock'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['stock']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="image" class="form-label">Ảnh Sản Phẩm</label>
                            <input type="file" class="form-control <?php echo isset($errors['image']) ? 'is-invalid' : ''; ?>" 
                                   id="image" name="image" accept="image/*">
                            <?php if (isset($errors['image'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['image']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Chấp nhận file JPG, PNG, GIF. Tối đa 5MB.</div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" 
                                       <?php echo isset($_POST['is_featured']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_featured">Sản phẩm nổi bật</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo !isset($_POST['is_active']) || $_POST['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Kích hoạt</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu Sản Phẩm
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

