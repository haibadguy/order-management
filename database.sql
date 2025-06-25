-- Create database with proper encoding
CREATE DATABASE IF NOT EXISTS order_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE order_management;

-- Create category table
CREATE TABLE IF NOT EXISTS category (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create product table
CREATE TABLE IF NOT EXISTS product (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2),
    stock INT NOT NULL DEFAULT 0,
    description TEXT,
    category_id INT,
    brand VARCHAR(100),
    image VARCHAR(255),
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES category(id)
);

-- Create customer table
CREATE TABLE IF NOT EXISTS customer (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    avatar VARCHAR(255),
    status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- Create employee table
CREATE TABLE IF NOT EXISTS employee (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    position VARCHAR(50),
    hire_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee') DEFAULT 'employee'
);

-- Create order table
CREATE TABLE IF NOT EXISTS `order` (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    employee_id INT,
    status ENUM('pending', 'confirmed', 'processing', 'shipping', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_code VARCHAR(50),
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cod', 'bank_transfer', 'credit_card', 'momo', 'zalopay') DEFAULT 'cod',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customer(id),
    FOREIGN KEY (employee_id) REFERENCES employee(id)
);

-- Create shipping_details table
CREATE TABLE IF NOT EXISTS shipping_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    shipping_method VARCHAR(50),
    shipping_fee DECIMAL(10,2) DEFAULT 0,
    shipping_address TEXT NOT NULL,
    estimated_delivery_date DATE,
    actual_delivery_date DATE,
    shipping_status ENUM('pending', 'processing', 'shipped', 'delivered', 'failed') DEFAULT 'pending',
    FOREIGN KEY (order_id) REFERENCES `order`(id) ON DELETE CASCADE
);

-- Create order_item table
CREATE TABLE IF NOT EXISTS order_item (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES `order`(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES product(id)
); 

-- Insert default admin user
INSERT INTO employee (full_name, email, phone, position, hire_date, password, role)
VALUES ('Admin User', 'admin@example.com', '1234567890', 'Administrator', CURDATE(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Default password is 'password'

-- -- phpMyAdmin SQL Dump
-- -- version 5.2.1
-- -- https://www.phpmyadmin.net/
-- --
-- -- Máy chủ: 127.0.0.1:3307
-- -- Thời gian đã tạo: Th6 25, 2025 lúc 06:19 AM
-- -- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- -- Phiên bản PHP: 8.2.12

-- SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
-- START TRANSACTION;
-- SET time_zone = "+00:00";


-- /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
-- /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
-- /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
-- /*!40101 SET NAMES utf8mb4 */;

-- --
-- -- Cơ sở dữ liệu: `order_management`
-- --

-- -- --------------------------------------------------------

-- --
-- -- Cấu trúc bảng cho bảng `category`
-- --

-- CREATE TABLE `category` (
--   `id` int(11) NOT NULL,
--   `name` varchar(100) NOT NULL,
--   `description` text DEFAULT NULL,
--   `image` varchar(255) DEFAULT NULL,
--   `is_active` tinyint(1) DEFAULT 1,
--   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
--   `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --
-- -- Đang đổ dữ liệu cho bảng `category`
-- --

-- INSERT INTO `category` (`id`, `name`, `description`, `image`, `is_active`, `created_at`, `updated_at`) VALUES
-- (1, 'Điện thoại', 'smartphone', 'uploads/categories/683d4438085a6.png', 1, '2025-06-01 14:18:40', '2025-06-02 06:27:04'),
-- (2, 'Laptop', 'máy tính xách tay', '', 1, '2025-06-02 12:02:34', '2025-06-02 12:02:34');

-- -- --------------------------------------------------------

-- --
-- -- Cấu trúc bảng cho bảng `customer`
-- --

-- CREATE TABLE `customer` (
--   `id` int(11) NOT NULL,
--   `full_name` varchar(100) NOT NULL,
--   `email` varchar(100) NOT NULL,
--   `phone` varchar(20) DEFAULT NULL,
--   `avatar` varchar(255) DEFAULT NULL,
--   `status` enum('active','inactive','blocked') DEFAULT 'active',
--   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
--   `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --
-- -- Đang đổ dữ liệu cho bảng `customer`
-- --

-- INSERT INTO `customer` (`id`, `full_name`, `email`, `phone`, `avatar`, `status`, `created_at`, `updated_at`) VALUES
-- (1, 'Nguyễn Hồng Hải', 'haicalisthenic132@gmail.com', '0393029196', 'uploads/avatars/683d911b5794b.jpg', 'active', '2025-06-01 14:20:30', '2025-06-02 11:55:07'),
-- (2, 'HongHai', 'hainh.b22cn268@stu.ptit.edu.vn', '0123456789', '', 'active', '2025-06-02 11:59:22', '2025-06-02 11:59:22'),
-- (3, 'quynh anh', 'hainh@gmail.com', '0393029196', '', 'active', '2025-06-02 14:15:37', '2025-06-02 14:15:37');

-- -- --------------------------------------------------------

-- --
-- -- Cấu trúc bảng cho bảng `detailed_address`
-- --

-- CREATE TABLE `detailed_address` (
--   `id` int(11) NOT NULL,
--   `shipping_detail_id` int(11) NOT NULL,
--   `province_code` int(11) NOT NULL,
--   `province_name` varchar(100) NOT NULL,
--   `district_code` int(11) NOT NULL,
--   `district_name` varchar(100) NOT NULL,
--   `ward_code` int(11) NOT NULL,
--   `ward_name` varchar(100) NOT NULL,
--   `street_address` text NOT NULL,
--   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
--   `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -- --------------------------------------------------------

-- --
-- -- Cấu trúc bảng cho bảng `employee`
-- --

-- CREATE TABLE `employee` (
--   `id` int(11) NOT NULL,
--   `full_name` varchar(100) NOT NULL,
--   `email` varchar(100) NOT NULL,
--   `phone` varchar(20) DEFAULT NULL,
--   `position` varchar(50) DEFAULT NULL,
--   `hire_date` date DEFAULT NULL,
--   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
--   `password` varchar(255) NOT NULL,
--   `role` enum('admin','employee') DEFAULT 'employee'
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --
-- -- Đang đổ dữ liệu cho bảng `employee`
-- --

-- INSERT INTO `employee` (`id`, `full_name`, `email`, `phone`, `position`, `hire_date`, `created_at`, `password`, `role`) VALUES
-- (1, 'Admin', 'admin@example.com', '1234567890', 'Administrator', '2025-06-01', '2025-06-01 13:37:04', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
-- (2, 'HongHai', 'hainh.b22cn268@stu.ptit.edu.vn', '0123456789', 'bán hàng', '2025-06-01', '2025-06-02 12:44:07', '$2y$10$OQqG0hbfVu0b/00iSELE9.u61u3euIqpTcmRAYDPc6KHXfLr/FClO', 'employee');

-- -- --------------------------------------------------------

-- --
-- -- Cấu trúc bảng cho bảng `order`
-- --

-- CREATE TABLE `order` (
--   `id` int(11) NOT NULL,
--   `customer_id` int(11) DEFAULT NULL,
--   `employee_id` int(11) DEFAULT NULL,
--   `status` enum('pending','confirmed','processing','shipping','completed','cancelled','refunded') DEFAULT 'pending',
--   `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
--   `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
--   `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
--   `discount_code` varchar(50) DEFAULT NULL,
--   `discount_percentage` decimal(5,2) DEFAULT 0.00,
--   `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
--   `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
--   `payment_method` enum('cod','bank_transfer','credit_card','momo','zalopay') DEFAULT 'cod',
--   `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
--   `notes` text DEFAULT NULL,
--   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
--   `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --
-- -- Đang đổ dữ liệu cho bảng `order`
-- --

-- INSERT INTO `order` (`id`, `customer_id`, `employee_id`, `status`, `subtotal`, `tax_amount`, `shipping_fee`, `discount_code`, `discount_percentage`, `discount_amount`, `total_amount`, `payment_method`, `payment_status`, `notes`, `created_at`, `updated_at`) VALUES
-- (24, 1, 1, 'completed', 47500000.00, 475000.00, 30000.00, NULL, 5.00, 0.00, 48005000.00, 'cod', 'paid', '', '2025-06-02 12:33:23', '2025-06-24 10:20:07'),
-- (25, 1, 1, 'completed', 46500000.00, 465000.00, 30000.00, NULL, 3.00, 0.00, 46995000.00, 'bank_transfer', 'paid', '', '2025-06-02 12:40:16', '2025-06-24 10:19:59'),
-- (26, 1, 1, 'completed', 68000000.00, 680000.00, 30000.00, NULL, 0.00, 0.00, 68710000.00, 'bank_transfer', 'paid', '', '2025-06-02 12:42:10', '2025-06-24 10:19:52'),
-- (27, 1, 1, 'completed', 92000000.00, 920000.00, 40000.00, NULL, 0.00, 0.00, 92960000.00, 'momo', 'paid', '', '2025-06-02 12:42:47', '2025-06-02 13:04:27'),
-- (28, 2, 1, 'completed', 70500000.00, 705000.00, 30000.00, NULL, 5.00, 0.00, 71235000.00, 'cod', 'paid', '', '2025-06-02 13:05:30', '2025-06-22 14:20:21'),
-- (29, 2, 1, 'completed', 70500000.00, 705000.00, 30000.00, NULL, 5.00, 0.00, 71235000.00, 'cod', 'paid', '', '2025-06-02 13:10:10', '2025-06-24 10:19:42'),
-- (30, 2, 1, 'completed', 73500000.00, 735000.00, 30000.00, NULL, 5.00, 0.00, 74265000.00, 'bank_transfer', 'paid', '', '2025-06-02 13:18:04', '2025-06-22 14:43:32'),
-- (31, 3, 1, 'processing', 70000000.00, 700000.00, 30000.00, NULL, 5.00, 0.00, 70730000.00, 'bank_transfer', 'paid', '', '2025-06-02 14:16:07', '2025-06-24 19:30:02'),
-- (32, 3, 1, 'shipping', 24000000.00, 240000.00, 30000.00, NULL, 0.00, 0.00, 24270000.00, 'cod', 'paid', '', '2025-06-22 14:21:13', '2025-06-24 19:29:52'),
-- (33, 1, 1, 'completed', 94000000.00, 940000.00, 30000.00, NULL, 10.00, 0.00, 94970000.00, 'momo', 'paid', '', '2025-06-22 14:50:42', '2025-06-22 14:51:02'),
-- (34, 3, 1, 'completed', 22000000.00, 220000.00, 30000.00, NULL, 3.00, 0.00, 22250000.00, 'bank_transfer', 'paid', '123', '2025-06-24 10:20:42', '2025-06-24 10:20:48');

-- -- --------------------------------------------------------

-- --
-- -- Cấu trúc bảng cho bảng `order_item`
-- --

-- CREATE TABLE `order_item` (
--   `id` int(11) NOT NULL,
--   `order_id` int(11) NOT NULL,
--   `product_id` int(11) NOT NULL,
--   `quantity` int(11) NOT NULL,
--   `unit_price` decimal(10,2) NOT NULL
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --
-- -- Đang đổ dữ liệu cho bảng `order_item`
-- --

-- INSERT INTO `order_item` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`) VALUES
-- (94, 27, 10, 1, 20000000.00),
-- (95, 27, 7, 1, 22000000.00),
-- (96, 27, 1, 1, 25000000.00),
-- (97, 27, 11, 1, 25000000.00),
-- (115, 28, 2, 1, 24500000.00),
-- (116, 28, 3, 1, 24000000.00),
-- (117, 28, 7, 1, 22000000.00),
-- (137, 30, 2, 1, 24500000.00),
-- (138, 30, 12, 2, 24500000.00),
-- (147, 33, 12, 1, 24500000.00),
-- (148, 33, 2, 1, 24500000.00),
-- (149, 33, 3, 1, 24000000.00),
-- (150, 33, 8, 1, 21000000.00),
-- (154, 29, 2, 1, 24500000.00),
-- (155, 29, 3, 1, 24000000.00),
-- (156, 29, 7, 1, 22000000.00),
-- (157, 26, 13, 1, 24000000.00),
-- (158, 26, 6, 1, 22000000.00),
-- (159, 26, 6, 1, 22000000.00),
-- (160, 25, 2, 1, 24500000.00),
-- (161, 25, 6, 1, 22000000.00),
-- (162, 24, 13, 1, 24000000.00),
-- (163, 24, 5, 1, 23500000.00),
-- (165, 34, 7, 1, 22000000.00),
-- (166, 32, 13, 1, 24000000.00),
-- (167, 31, 12, 1, 24500000.00),
-- (168, 31, 2, 1, 24500000.00),
-- (169, 31, 8, 1, 21000000.00);

-- -- --------------------------------------------------------

-- --
-- -- Cấu trúc bảng cho bảng `product`
-- --

-- CREATE TABLE `product` (
--   `id` int(11) NOT NULL,
--   `name` varchar(100) NOT NULL,
--   `price` decimal(10,2) NOT NULL,
--   `sale_price` decimal(10,2) DEFAULT NULL,
--   `stock` int(11) NOT NULL DEFAULT 0,
--   `description` text DEFAULT NULL,
--   `category_id` int(11) DEFAULT NULL,
--   `brand` varchar(100) DEFAULT NULL,
--   `image` varchar(255) DEFAULT NULL,
--   `is_featured` tinyint(1) DEFAULT 0,
--   `is_active` tinyint(1) DEFAULT 1,
--   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
--   `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --
-- -- Đang đổ dữ liệu cho bảng `product`
-- --

-- INSERT INTO `product` (`id`, `name`, `price`, `sale_price`, `stock`, `description`, `category_id`, `brand`, `image`, `is_featured`, `is_active`, `created_at`, `updated_at`) VALUES
-- (1, 'Iphone 16 pro max', 25000000.00, 25000000.00, 89, '512GB', 1, 'Apple', 'uploads/products/683d7f6ee6fa6.png', 0, 1, '2025-06-01 14:19:21', '2025-06-02 13:04:27'),
-- (2, 'Iphone 16 pro', 24500000.00, 24000000.00, 68, '512GB', 1, 'Apple', 'uploads/products/683d7f75b686d.png', 0, 1, '2025-06-01 14:19:58', '2025-06-24 19:30:02'),
-- (3, 'Iphone 16 plus', 24000000.00, 23500000.00, 83, '512GB', 1, 'Apple', 'uploads/products/683d7f7c3546b.png', 0, 1, '2025-06-02 06:40:48', '2025-06-24 10:19:42'),
-- (5, 'Iphone 16', 23500000.00, 23000000.00, 89, '512GB', 1, 'Apple', 'uploads/products/683d7f832ef43.png', 0, 1, '2025-06-02 06:41:59', '2025-06-24 10:20:07'),
-- (6, 'Iphone 15 pro max', 22000000.00, 22000000.00, 88, '512GB', 1, 'Apple', 'uploads/products/683d7f8905ca3.png', 0, 1, '2025-06-02 06:43:03', '2025-06-24 10:19:59'),
-- (7, 'Iphone 15 pro', 22000000.00, 21000000.00, 81, '512GB', 1, 'Apple', 'uploads/products/683d7f8f94e80.png', 0, 1, '2025-06-02 06:43:37', '2025-06-24 10:20:48'),
-- (8, 'Iphone 15 plus', 21000000.00, 20000000.00, 88, '512gb', 1, 'Apple', 'uploads/products/683d7f95932d8.png', 0, 1, '2025-06-02 06:44:03', '2025-06-24 19:30:02'),
-- (10, 'Iphone 15', 20000000.00, 19500000.00, 88, '512GB', 1, 'Apple', 'uploads/products/683d7f9b18c5c.png', 0, 1, '2025-06-02 06:44:42', '2025-06-02 13:04:27'),
-- (11, 'Samsung Galaxy S25 Edge', 25000000.00, 25000000.00, 93, '512GB', 1, 'SamSung', '', 0, 1, '2025-06-02 11:39:28', '2025-06-02 13:04:27'),
-- (12, 'Samsung Galaxy S25 Plus', 24500000.00, 24500000.00, 74, '512gb', 1, 'SamSung', '', 0, 1, '2025-06-02 11:39:59', '2025-06-24 19:30:02'),
-- (13, 'Samsung Galaxy S25', 24000000.00, 24000000.00, 86, '512gb', 1, 'SamSung', '', 0, 1, '2025-06-02 11:40:26', '2025-06-24 19:29:52');

-- -- --------------------------------------------------------

-- --
-- -- Cấu trúc bảng cho bảng `shipping_details`
-- --

-- CREATE TABLE `shipping_details` (
--   `id` int(11) NOT NULL,
--   `order_id` int(11) NOT NULL,
--   `shipping_method` varchar(50) DEFAULT NULL,
--   `shipping_fee` decimal(10,2) DEFAULT 0.00,
--   `shipping_address` text DEFAULT NULL,
--   `province_code` int(11) DEFAULT NULL,
--   `district_code` int(11) DEFAULT NULL,
--   `ward_code` int(11) DEFAULT NULL,
--   `street_address` text DEFAULT NULL,
--   `estimated_delivery_date` date DEFAULT NULL,
--   `actual_delivery_date` date DEFAULT NULL,
--   `shipping_status` enum('pending','processing','shipped','delivered','failed') DEFAULT 'pending'
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --
-- -- Đang đổ dữ liệu cho bảng `shipping_details`
-- --

-- INSERT INTO `shipping_details` (`id`, `order_id`, `shipping_method`, `shipping_fee`, `shipping_address`, `province_code`, `district_code`, `ward_code`, `street_address`, `estimated_delivery_date`, `actual_delivery_date`, `shipping_status`) VALUES
-- (23, 24, 'standard', 30000.00, 'số 5, Xã Vĩnh Lại, Huyện Lâm Thao, Tỉnh Phú Thọ', 25, 237, 8533, 'số 5', '2025-06-05', NULL, ''),
-- (24, 25, 'express', 30000.00, '1, Xã Thái Học, Huyện Bảo Lâm, Tỉnh Cao Bằng', 4, 42, 1315, '1', '2025-06-05', NULL, ''),
-- (25, 26, 'express', 30000.00, '1, Xã Tiên Kiên, Huyện Lâm Thao, Tỉnh Phú Thọ', 25, 237, 8497, '1', '2025-06-05', NULL, ''),
-- (26, 27, 'same_day', 40000.00, '1, Phường Xuân Tảo, Quận Bắc Từ Liêm, Thành phố Hà Nội', 1, 21, 611, '1', '2025-06-05', NULL, ''),
-- (27, 28, 'standard', 30000.00, 'số 2, Phường Đồng Nguyên, Thành phố Từ Sơn, Tỉnh Bắc Ninh', 27, 261, 9385, 'số 2', '2025-06-05', NULL, ''),
-- (28, 29, 'standard', 30000.00, 'số 2, Phường Đồng Nguyên, Thành phố Từ Sơn, Tỉnh Bắc Ninh', 27, 261, 9385, NULL, '2025-06-05', NULL, ''),
-- (29, 30, 'express', 30000.00, 'sa, Xã Long Châu, Huyện Yên Phong, Tỉnh Bắc Ninh', 27, 258, 9232, 'sa', '2025-06-05', NULL, ''),
-- (30, 31, 'express', 30000.00, 'số 5, Phường Quang Trung, Thành phố Hà Giang, Tỉnh Hà Giang', 2, 24, 688, 'số 5', '2025-06-05', NULL, ''),
-- (31, 32, 'express', 30000.00, 'số 2, Phường Vân Trung, Thị xã Việt Yên, Tỉnh Bắc Giang', 24, 222, 7801, 'số 2', '2025-06-25', NULL, ''),
-- (32, 33, 'express', 30000.00, 'số 5, Phường Tràng Tiền, Quận Hoàn Kiếm, Thành phố Hà Nội', 1, 2, 79, 'số 5', '2025-06-25', NULL, ''),
-- (33, 34, 'express', 30000.00, '11, Xã Thái Học, Huyện Bảo Lâm, Tỉnh Cao Bằng', 4, 42, 1315, '11', '2025-06-27', NULL, '');

-- --
-- -- Chỉ mục cho các bảng đã đổ
-- --

-- --
-- -- Chỉ mục cho bảng `category`
-- --
-- ALTER TABLE `category`
--   ADD PRIMARY KEY (`id`);

-- --
-- -- Chỉ mục cho bảng `customer`
-- --
-- ALTER TABLE `customer`
--   ADD PRIMARY KEY (`id`),
--   ADD UNIQUE KEY `email` (`email`);

-- --
-- -- Chỉ mục cho bảng `detailed_address`
-- --
-- ALTER TABLE `detailed_address`
--   ADD PRIMARY KEY (`id`),
--   ADD KEY `shipping_detail_id` (`shipping_detail_id`);

-- --
-- -- Chỉ mục cho bảng `employee`
-- --
-- ALTER TABLE `employee`
--   ADD PRIMARY KEY (`id`),
--   ADD UNIQUE KEY `email` (`email`);

-- --
-- -- Chỉ mục cho bảng `order`
-- --
-- ALTER TABLE `order`
--   ADD PRIMARY KEY (`id`),
--   ADD KEY `customer_id` (`customer_id`),
--   ADD KEY `employee_id` (`employee_id`);

-- --
-- -- Chỉ mục cho bảng `order_item`
-- --
-- ALTER TABLE `order_item`
--   ADD PRIMARY KEY (`id`),
--   ADD KEY `order_id` (`order_id`),
--   ADD KEY `product_id` (`product_id`);

-- --
-- -- Chỉ mục cho bảng `product`
-- --
-- ALTER TABLE `product`
--   ADD PRIMARY KEY (`id`),
--   ADD KEY `category_id` (`category_id`);

-- --
-- -- Chỉ mục cho bảng `shipping_details`
-- --
-- ALTER TABLE `shipping_details`
--   ADD PRIMARY KEY (`id`),
--   ADD KEY `order_id` (`order_id`);

-- --
-- -- AUTO_INCREMENT cho các bảng đã đổ
-- --

-- --
-- -- AUTO_INCREMENT cho bảng `category`
-- --
-- ALTER TABLE `category`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

-- --
-- -- AUTO_INCREMENT cho bảng `customer`
-- --
-- ALTER TABLE `customer`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

-- --
-- -- AUTO_INCREMENT cho bảng `detailed_address`
-- --
-- ALTER TABLE `detailed_address`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

-- --
-- -- AUTO_INCREMENT cho bảng `employee`
-- --
-- ALTER TABLE `employee`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

-- --
-- -- AUTO_INCREMENT cho bảng `order`
-- --
-- ALTER TABLE `order`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

-- --
-- -- AUTO_INCREMENT cho bảng `order_item`
-- --
-- ALTER TABLE `order_item`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=170;

-- --
-- -- AUTO_INCREMENT cho bảng `product`
-- --
-- ALTER TABLE `product`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

-- --
-- -- AUTO_INCREMENT cho bảng `shipping_details`
-- --
-- ALTER TABLE `shipping_details`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

-- --
-- -- Các ràng buộc cho các bảng đã đổ
-- --

-- --
-- -- Các ràng buộc cho bảng `detailed_address`
-- --
-- ALTER TABLE `detailed_address`
--   ADD CONSTRAINT `detailed_address_ibfk_1` FOREIGN KEY (`shipping_detail_id`) REFERENCES `shipping_details` (`id`) ON DELETE CASCADE;

-- --
-- -- Các ràng buộc cho bảng `order`
-- --
-- ALTER TABLE `order`
--   ADD CONSTRAINT `order_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`),
--   ADD CONSTRAINT `order_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`);

-- --
-- -- Các ràng buộc cho bảng `order_item`
-- --
-- ALTER TABLE `order_item`
--   ADD CONSTRAINT `order_item_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `order_item_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`);

-- --
-- -- Các ràng buộc cho bảng `product`
-- --
-- ALTER TABLE `product`
--   ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`);

-- --
-- -- Các ràng buộc cho bảng `shipping_details`
-- --
-- ALTER TABLE `shipping_details`
--   ADD CONSTRAINT `shipping_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE;
-- COMMIT;

-- /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
-- /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
-- /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
