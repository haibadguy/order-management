<?php
require_once '../includes/auth.php';

// Kiểm tra quyền admin
requireAdmin();

// Kiểm tra ID nhân viên
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setMessage('ID nhân viên không hợp lệ', 'danger');
    header('Location: index.php');
    exit();
}

// Lấy thông tin nhân viên
try {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM employee WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $employee = mysqli_fetch_assoc($result);

    if (!$employee) {
        setMessage('Không tìm thấy nhân viên', 'danger');
        header('Location: index.php');
        exit();
    }
} catch (Exception $e) {
    setMessage('Lỗi khi lấy thông tin nhân viên: ' . $e->getMessage(), 'danger');
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
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    // Validate dữ liệu
    if (empty($full_name)) {
        $errors['full_name'] = 'Vui lòng nhập tên nhân viên';
    }

    if (empty($email)) {
        $errors['email'] = 'Vui lòng nhập email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ';
    } else {
        // Kiểm tra email đã tồn tại chưa (trừ email hiện tại)
        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM employee WHERE email = ? AND id != ?');
        mysqli_stmt_bind_param($stmt, 'si', $email, $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        if ($row['total'] > 0) {
            $errors['email'] = 'Email này đã được sử dụng';
        }
    }

    if (empty($phone)) {
        $errors['phone'] = 'Vui lòng nhập số điện thoại';
    } elseif (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        $errors['phone'] = 'Số điện thoại không hợp lệ';
    }

    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự';
        } elseif ($password !== $confirm_password) {
            $errors['confirm_password'] = 'Mật khẩu xác nhận không khớp';
        }
    }

    if (empty($role)) {
        $errors['role'] = 'Vui lòng chọn vai trò';
    } elseif (!in_array($role, ['admin', 'employee'])) {
        $errors['role'] = 'Vai trò không hợp lệ';
    }

    // Nếu không có lỗi thì cập nhật nhân viên
    if (empty($errors)) {
        try {
            if (!empty($password)) {
                // Cập nhật cả mật khẩu
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, '
                    UPDATE employee 
                    SET full_name = ?, email = ?, phone = ?, password = ?, role = ?
                    WHERE id = ?
                ');
                mysqli_stmt_bind_param($stmt, 'sssssi', $full_name, $email, $phone, $hashed_password, $role, $id);
                mysqli_stmt_execute($stmt);
            } else {
                // Không cập nhật mật khẩu
                $stmt = mysqli_prepare($conn, '
                    UPDATE employee 
                    SET full_name = ?, email = ?, phone = ?, role = ?
                    WHERE id = ?
                ');
                mysqli_stmt_bind_param($stmt, 'ssssi', $full_name, $email, $phone, $role, $id);
                mysqli_stmt_execute($stmt);
            }

            setMessage('Cập nhật nhân viên thành công');
            header('Location: index.php');
            exit();
        } catch (Exception $e) {
            $errors['db'] = 'Lỗi khi cập nhật nhân viên: ' . $e->getMessage();
        }
    }
}

// Thiết lập tiêu đề trang
$page_title = "Sửa Nhân Viên";
require_once '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Sửa Nhân Viên</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay Lại
        </a>
    </div>

    <?php if (isset($errors['db'])): ?>
        <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="mb-3">
                    <label for="full_name" class="form-label">Tên Nhân Viên</label>
                    <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                           id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars(isset($_POST['full_name']) ? $_POST['full_name'] : ($employee['full_name'] ?? '')); ?>" required>
                    <?php if (isset($errors['full_name'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                           id="email" name="email" 
                           value="<?php echo htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : ($employee['email'] ?? '')); ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Số Điện Thoại</label>
                    <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                           id="phone" name="phone" 
                           value="<?php echo htmlspecialchars(isset($_POST['phone']) ? $_POST['phone'] : ($employee['phone'] ?? '')); ?>" required>
                    <?php if (isset($errors['phone'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mật Khẩu Mới</label>
                    <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                           id="password" name="password">
                    <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                    <?php endif; ?>
                    <div class="form-text">Để trống nếu không muốn thay đổi mật khẩu</div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Xác Nhận Mật Khẩu Mới</label>
                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                           id="confirm_password" name="confirm_password">
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Vai Trò</label>
                    <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" 
                            id="role" name="role" required>
                        <option value="">Chọn vai trò</option>
                        <option value="admin" <?php echo ($_POST['role'] ?? $employee['role']) === 'admin' ? 'selected' : ''; ?>>Quản Trị</option>
                        <option value="employee" <?php echo ($_POST['role'] ?? $employee['role']) === 'employee' ? 'selected' : ''; ?>>Nhân Viên</option>
                    </select>
                    <?php if (isset($errors['role'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['role']; ?></div>
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

<?php require_once '../includes/footer.php'; ?> 