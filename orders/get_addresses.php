<?php
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Kiểm tra customer_id
$customer_id = intval($_GET['customer_id'] ?? 0);
if (!$customer_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID is required']);
    exit();
}

try {
    // Lấy danh sách địa chỉ của khách hàng
    $stmt = mysqli_prepare($conn, 'SELECT * FROM customer_address WHERE customer_id = ? ORDER BY is_default DESC');
    mysqli_stmt_bind_param($stmt, 'i', $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $addresses = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    echo json_encode($addresses);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 