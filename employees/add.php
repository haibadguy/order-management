<?php
require_once '../includes/auth.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    validateCSRFToken($_POST['csrf_token'] ?? '');

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $hire_date = trim($_POST['hire_date'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if (empty($full_name)) {
        $errors['full_name'] = 'Vui lòng nhập tên nhân viên';
    }

    if (empty($email)) {
        $errors['email'] = 'Vui lòng nhập email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) as total FROM employee WHERE email = ?');
        mysqli_stmt_bind_param($stmt, 's', $email);
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

    if (empty($position)) {
        $errors['position'] = 'Vui lòng nhập chức vụ';
    }

    if (empty($hire_date)) {
        $errors['hire_date'] = 'Vui lòng chọn ngày vào làm';
    } elseif (strtotime($hire_date) > time()) {
        $errors['hire_date'] = 'Ngày vào làm không thể là ngày trong tương lai';
    }

    if (empty($password)) {
        $errors['password'] = 'Vui lòng nhập mật khẩu';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự';
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Mật khẩu xác nhận không khớp';
    }

    if (empty($role)) {
        $errors['role'] = 'Vui lòng chọn vai trò';
    } elseif (!in_array($role, ['admin', 'employee'])) {
        $errors['role'] = 'Vai trò không hợp lệ';
    }

    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, '
                INSERT INTO employee (full_name, email, phone, position, hire_date, password, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            mysqli_stmt_bind_param($stmt, 'sssssss', 
                $full_name, $email, $phone, $position, $hire_date, $hashed_password, $role
            );
            mysqli_stmt_execute($stmt);

            setMessage('Thêm nhân viên thành công');
            header('Location: index.php');
            exit();
        } catch (Exception $e) {
            $errors['db'] = 'Lỗi khi thêm nhân viên: ' . $e->getMessage();
        }
    }
}

$page_title = "Thêm Nhân Viên";
require_once '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Nhân Viên - Hệ Thống Quản Lý Đơn Hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo BASE_PATH; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>


    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Thêm Nhân Viên Mới</h2>
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

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Tên Nhân Viên</label>
                                <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                       id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                <?php if (isset($errors['full_name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                       id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Số Điện Thoại</label>
                                <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                       id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="position" class="form-label">Chức Vụ</label>
                                <input type="text" class="form-control <?php echo isset($errors['position']) ? 'is-invalid' : ''; ?>" 
                                       id="position" name="position" value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>" required>
                                <?php if (isset($errors['position'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['position']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hire_date" class="form-label">Ngày Vào Làm</label>
                                <input type="date" class="form-control <?php echo isset($errors['hire_date']) ? 'is-invalid' : ''; ?>" 
                                       id="hire_date" name="hire_date" value="<?php echo htmlspecialchars($_POST['hire_date'] ?? ''); ?>" required>
                                <?php if (isset($errors['hire_date'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['hire_date']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Mật Khẩu</label>
                                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                       id="password" name="password" required>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Xác Nhận Mật Khẩu</label>
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                       id="confirm_password" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">Vai Trò</label>
                                <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" 
                                        id="role" name="role" required>
                                    <option value="">Chọn vai trò</option>
                                    <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Quản Trị</option>
                                    <option value="employee" <?php echo ($_POST['role'] ?? '') === 'employee' ? 'selected' : ''; ?>>Nhân Viên</option>
                                </select>
                                <?php if (isset($errors['role'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['role']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Lưu Nhân Viên
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html> 