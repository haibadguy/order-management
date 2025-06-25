<?php
// Cấu hình thư mục upload
define('UPLOAD_DIR', __DIR__ . '/../uploads');

// Tạo thư mục upload nếu chưa tồn tại
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
if (!file_exists(UPLOAD_DIR . '/products')) {
    mkdir(UPLOAD_DIR . '/products', 0777, true);
}
if (!file_exists(UPLOAD_DIR . '/categories')) {
    mkdir(UPLOAD_DIR . '/categories', 0777, true);
}

/**
 * Upload file ảnh
 * @param array $file File từ $_FILES hoặc URL ảnh
 * @param string $subdir Thư mục con (products/categories)
 * @return string|false Đường dẫn tương đối của file hoặc false nếu lỗi
 */
function uploadImage($file, $subdir = 'products') {
    // Nếu là URL
    if (is_string($file) && filter_var($file, FILTER_VALIDATE_URL)) {
        $image_data = @file_get_contents($file);
        if ($image_data === false) {
            return false;
        }
        
        // Lấy extension từ Content-Type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image_data);
        $ext = str_replace('image/', '', $mime_type);
        
        // Tạo tên file mới
        $filename = uniqid() . '.' . $ext;
        $filepath = UPLOAD_DIR . '/' . $subdir . '/' . $filename;
        
        // Lưu file
        if (file_put_contents($filepath, $image_data)) {
            return '/uploads/' . $subdir . '/' . $filename;
        }
        return false;
    }
    
    // Nếu là file upload
    if (is_array($file)) {
        // Kiểm tra lỗi upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Kiểm tra loại file
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            return false;
        }

        // Tạo tên file mới
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        
        // Đường dẫn đầy đủ
        $filepath = UPLOAD_DIR . '/' . $subdir . '/' . $filename;
        
        // Di chuyển file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return '/uploads/' . $subdir . '/' . $filename;
        }
    }
    
    return false;
}

/**
 * Xóa file ảnh
 * @param string $path Đường dẫn tương đối của file
 * @return bool
 */
function deleteImage($path) {
    if (!$path) return true;
    
    $fullpath = __DIR__ . '/..' . $path;
    if (file_exists($fullpath)) {
        return unlink($fullpath);
    }
    return true;
}

/**
 * Lấy đường dẫn đầy đủ của ảnh
 * @param string $path Đường dẫn tương đối hoặc URL
 * @return string
 */
function getImageUrl($path) {
    if (!$path) {
        return 'https://placehold.co/600x400?text=No+Image';
    }
    
    // Nếu là URL đầy đủ
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }
    
    // Nếu là đường dẫn local
    $fullpath = __DIR__ . '/..' . $path;
    if (file_exists($fullpath)) {
        return $path;
    }
    
    // Trả về ảnh placeholder nếu không tìm thấy
    return 'https://placehold.co/600x400?text=No+Image';
} 