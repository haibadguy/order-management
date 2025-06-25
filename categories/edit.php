<?php
require_once '../includes/auth.php';

// Kiểm tra quyền admin
requireAdmin();

// Kiểm tra ID danh mục
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID danh mục không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

// Lấy thông tin danh mục
try {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM category WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $category = mysqli_fetch_assoc($result);

    if (!$category) {
        setMessage('Không tìm thấy danh mục', 'danger');
        header('Location: index.php');
        exit();
    }
} catch (Exception $e) {
    setMessage('Lỗi khi lấy thông tin danh mục: ' . $e->getMessage(), 'danger');
    header('Location: index.php');
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    validateCSRFToken($_POST['csrf_token'] ?? '');

    // Lấy dữ liệu từ form
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Xử lý upload hình ảnh
    $image = $category['image']; // Giữ lại hình ảnh cũ nếu không upload mới
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
            $upload_dir = '../uploads/categories/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                // Xóa file ảnh cũ nếu có
                if ($category['image'] && file_exists('../' . $category['image'])) {
                    unlink('../' . $category['image']);
                }
                $image = 'uploads/categories/' . $filename;
            } else {
                $errors['image'] = 'Không thể upload file. Vui lòng thử lại';
            }
        }
    }

    // Validate dữ liệu
    if (empty($name)) {
        $errors['name'] = 'Vui lòng nhập tên danh mục';
    } else {
        // Kiểm tra danh mục đã tồn tại chưa (trừ danh mục hiện tại)
        try {
            $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM category WHERE name = ? AND id != ?');
            mysqli_stmt_bind_param($stmt, 'si', $name, $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            if ($row['total'] > 0) {
                $errors['name'] = 'Danh mục này đã tồn tại';
            }
        } catch (Exception $e) {
            $errors['db'] = 'Lỗi khi kiểm tra danh mục: ' . $e->getMessage();
        }
    }

    // Nếu không có lỗi thì cập nhật danh mục
    if (empty($errors)) {
        try {
            $stmt = mysqli_prepare($conn, 'UPDATE category SET name = ?, description = ?, image = ?, is_active = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'sssii', $name, $description, $image, $is_active, $id);
            mysqli_stmt_execute($stmt);

            setMessage('Cập nhật danh mục thành công');
            header('Location: index.php');
            exit();
        } catch (Exception $e) {
            $errors['db'] = 'Lỗi khi cập nhật danh mục: ' . $e->getMessage();
        }
    }
}

// Thiết lập tiêu đề trang
$page_title = "Sửa Danh Mục";
require_once '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Sửa Danh Mục</h2>
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

                <div class="mb-3">
                    <label for="name" class="form-label">Tên Danh Mục</label>
                    <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                           id="name" name="name" 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? $category['name']); ?>" required>
                    <?php if (isset($errors['name'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Mô Tả</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? $category['description']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Hình Ảnh</label>
                    <?php if ($category['image']): ?>
                        <div class="mb-2">
                            <img src="<?php echo htmlspecialchars('../' . $category['image']); ?>" 
                                 alt="Current image" class="img-thumbnail" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control <?php echo isset($errors['image']) ? 'is-invalid' : ''; ?>" 
                           id="image" name="image" accept="image/*">
                    <?php if (isset($errors['image'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['image']; ?></div>
                    <?php endif; ?>
                    <div class="form-text">Chấp nhận file JPG, PNG, GIF. Tối đa 5MB.</div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" 
                               <?php echo (isset($_POST['is_active']) ? $_POST['is_active'] : $category['is_active']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Kích hoạt</label>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu Thay Đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
