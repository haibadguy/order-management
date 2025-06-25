<?php
require_once 'includes/auth.php';

// Nếu chưa đăng nhập thì chuyển đến trang login
if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

// Nếu đã đăng nhập thì chuyển đến trang dashboard
header('Location: dashboard/index.php');
exit();
