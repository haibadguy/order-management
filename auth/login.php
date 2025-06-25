<?php
require_once '../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ../dashboard/index.php');
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate
    if (empty($email)) {
        $errors['email'] = 'Vui lòng nhập email';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Vui lòng nhập mật khẩu';
    }
    
    if (empty($errors)) {
        if (doLogin($email, $password)) {
            header('Location: ../dashboard/index.php');
            exit();
        } else {
            $errors['login'] = 'Email hoặc mật khẩu không đúng';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - TechOrder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header i {
            font-size: 48px;
            color: #3a36e0;
            margin-bottom: 15px;
        }
        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #2d3436;
            margin: 0;
        }
        .form-floating {
            margin-bottom: 20px;
        }
        .btn-login {
            background: #3a36e0;
            border: none;
            padding: 15px;
            font-weight: 500;
            width: 100%;
        }
        .btn-login:hover {
            background: #2825b3;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-cube"></i>
            <h1>TechOrder</h1>
            <p class="text-muted">Hệ thống quản lý đơn hàng</p>
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

        <form method="POST" action="">
            <div class="form-floating">
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="Email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                <label for="email">Email</label>
            </div>

            <div class="form-floating">
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Mật khẩu" required>
                <label for="password">Mật khẩu</label>
            </div>

            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Đăng nhập
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 