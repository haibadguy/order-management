<?php
require_once '../includes/auth.php';

// Kiểm tra quyền admin
requireAdmin();

// Kiểm tra ID khách hàng
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID khách hàng không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

// Lấy thông tin khách hàng
try {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM customer WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $customer = mysqli_fetch_assoc($result);

    if (!$customer) {
        setMessage('Không tìm thấy khách hàng', 'danger');
        header('Location: index.php');
        exit();
    }
} catch (Exception $e) {
    setMessage('Lỗi khi lấy thông tin khách hàng: ' . $e->getMessage(), 'danger');
    header('Location: index.php');
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    validateCSRFToken($_POST['csrf_token'] ?? '');

    // Lấy dữ liệu từ form
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';

    // Xử lý upload avatar
    $avatar = $customer['avatar']; // Giữ lại avatar cũ nếu không upload mới
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file = $_FILES['avatar'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors['avatar'] = 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF)';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $errors['avatar'] = 'Kích thước file không được vượt quá 5MB';
        } else {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $upload_dir = '../uploads/avatars/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                // Xóa avatar cũ nếu có
                if ($customer['avatar'] && file_exists('../' . $customer['avatar'])) {
                    unlink('../' . $customer['avatar']);
                }
                $avatar = 'uploads/avatars/' . $filename;
            } else {
                $errors['avatar'] = 'Không thể upload file. Vui lòng thử lại';
            }
        }
    }

    // Validate dữ liệu
    if (empty($full_name)) {
        $errors['full_name'] = 'Vui lòng nhập họ tên';
    }

    if (empty($email)) {
        $errors['email'] = 'Vui lòng nhập email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ';
    } else {
        // Kiểm tra email đã tồn tại chưa (trừ email hiện tại)
        try {
            $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM customer WHERE email = ? AND id != ?');
            mysqli_stmt_bind_param($stmt, 'si', $email, $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            if ($row['total'] > 0) {
                $errors['email'] = 'Email này đã được sử dụng';
            }
        } catch (Exception $e) {
            $errors['db'] = 'Lỗi khi kiểm tra email: ' . $e->getMessage();
        }
    }

    if (!empty($phone) && !preg_match('/^[0-9]{10,11}$/', $phone)) {
        $errors['phone'] = 'Số điện thoại không hợp lệ';
    }

    if (!in_array($status, ['active', 'inactive', 'blocked'])) {
        $errors['status'] = 'Trạng thái không hợp lệ';
    }

    // Nếu không có lỗi thì cập nhật khách hàng
    if (empty($errors)) {
        try {
            $stmt = mysqli_prepare($conn, 'UPDATE customer SET full_name = ?, email = ?, phone = ?, avatar = ?, status = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'sssssi', $full_name, $email, $phone, $avatar, $status, $id);
            mysqli_stmt_execute($stmt);

            setMessage('Cập nhật khách hàng thành công');
            header('Location: index.php');
            exit();
        } catch (Exception $e) {
            $errors['db'] = 'Lỗi khi cập nhật khách hàng: ' . $e->getMessage();
        }
    }
}

// Thiết lập tiêu đề trang
$page_title = "Sửa Khách Hàng";
require_once '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Sửa Thông Tin Khách Hàng</h2>
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
                    <label for="full_name" class="form-label">Họ Tên</label>
                    <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                           id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? $customer['full_name']); ?>" required>
                    <?php if (isset($errors['full_name'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                           id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? $customer['email']); ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Số Điện Thoại</label>
                    <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                           id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? $customer['phone']); ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="avatar" class="form-label">Ảnh Đại Diện</label>
                    <?php if ($customer['avatar']): ?>
                        <div class="mb-2">
                            <img src="<?php echo htmlspecialchars('../' . $customer['avatar']); ?>" 
                                 alt="Current avatar" class="img-thumbnail" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control <?php echo isset($errors['avatar']) ? 'is-invalid' : ''; ?>" 
                           id="avatar" name="avatar" accept="image/*">
                    <?php if (isset($errors['avatar'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['avatar']; ?></div>
                    <?php endif; ?>
                    <div class="form-text">Chấp nhận file JPG, PNG, GIF. Tối đa 5MB.</div>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Trạng Thái</label>
                    <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                            id="status" name="status">
                        <option value="active" <?php echo (isset($_POST['status']) ? $_POST['status'] : $customer['status']) === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                        <option value="inactive" <?php echo (isset($_POST['status']) ? $_POST['status'] : $customer['status']) === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                        <option value="blocked" <?php echo (isset($_POST['status']) ? $_POST['status'] : $customer['status']) === 'blocked' ? 'selected' : ''; ?>>Đã khóa</option>
                    </select>
                    <?php if (isset($errors['status'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['status']; ?></div>
                    <?php endif; ?>
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
