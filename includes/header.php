<?php
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>TechOrder - Hệ Thống Quản Lý Đơn Hàng</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" />
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/jquery.dataTables.min.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/sidebar.css">
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <script>
        // Lưu vị trí scroll của sidebar khi chuyển trang
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            
            // Khôi phục vị trí scroll từ localStorage
            const savedScrollPosition = localStorage.getItem('sidebarScrollPosition');
            if (savedScrollPosition) {
                sidebar.scrollTop = parseInt(savedScrollPosition);
            }
            
            // Lưu vị trí scroll vào localStorage khi scroll
            sidebar.addEventListener('scroll', function() {
                localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
            });
            
            // Lưu vị trí scroll trước khi chuyển trang
            document.querySelectorAll('.menu-item').forEach(link => {
                link.addEventListener('click', function() {
                    localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
                });
            });
        });
    </script>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-content"> 