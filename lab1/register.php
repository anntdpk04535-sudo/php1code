
-- ========================================================
-- 2. TẠO BẢNG DANH MỤC SẢN PHẨM (Tạo trước để làm khóa ngoại)
-- ========================================================
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. TẠO BẢNG NGƯỜI DÙNG (Users)
-- --------------------------------------------------------
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Tên tài khoản đăng nhập',
    `fullname` VARCHAR(100) NOT NULL COMMENT 'Họ và tên đầy đủ',
    `email` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Dùng làm email khôi phục hoặc đăng nhập',
    `password` VARCHAR(255) NOT NULL COMMENT 'Mật khẩu mã hóa bằng password_hash',
    `role` VARCHAR(20) DEFAULT 'user' COMMENT 'admin hoặc user',
    `status` TINYINT(1) DEFAULT 1 COMMENT '1: Hoạt động, 0: Bị khóa',
    `token_reset` VARCHAR(255) NULL,
    `token_expiry` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. TẠO BẢNG SẢN PHẨM (Products)
-- --------------------------------------------------------
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NULL,
    `name` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `quantity` INT NOT NULL DEFAULT 0,
    `image` VARCHAR(255) NULL COMMENT 'Lưu tên file hình ảnh sản phẩm',
    `description` TEXT NULL,
    `status` TINYINT(1) DEFAULT 1 COMMENT '1: Hiển thị, 0: Ẩn',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tạo chỉ mục (Index) hỗ trợ tăng tốc tính năng tìm kiếm nâng cao theo tên
ALTER TABLE `products` ADD INDEX `idx_product_name` (`name`);

-- ========================================================
-- 5. CHÈN DỮ LIỆU MẪU BAN ĐẦU (Mật khẩu mặc định: 123456)
-- ========================================================

-- Chèn dữ liệu bảng Categories
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Điện thoại & Máy tính', 'Các thiết bị công nghệ điện tử'),
(2, 'Thời trang', 'Quần áo, giày dép nam nữ'),
(3, 'Gia dụng', 'Đồ dùng nhà bếp và gia đình');

-- Chèn dữ liệu bảng Users
INSERT INTO `users` (`username`, `fullname`, `email`, `password`, `role`, `status`) VALUES
('admin', 'Quản Trị Viên', 'admin@novamart.com', '$2y$10$fWvG8050E63C2lYsh8KKe.gU1L64B3PZ7fA47ZJ0rS2w7gVpbeJtG', 'admin', 1),
('user', 'Nguyễn Văn A', 'user@gmail.com', '$2y$10$fWvG8050E63C2lYsh8KKe.gU1L64B3PZ7fA47ZJ0rS2w7gVpbeJtG', 'user', 1);

-- Chèn dữ liệu bảng Products
INSERT INTO `products` (`category_id`, `name`, `price`, `quantity`, `image`, `description`) VALUES
(1, 'iPhone 15 Pro Max', 29990000.00, 15, 'iphone15.jpg', 'Điện thoại Apple đời mới nhất'),
(1, 'Samsung Galaxy S24 Ultra', 26990000.00, 10, 's24.jpg', 'Flagship đỉnh cao của Samsung'),
(2, 'Áo Khoác Blazer Nam', 450000.00, 50, 'blazer.jpg', 'Phong cách lịch lãm Hàn Quốc'),
(2, 'Giày Thể Thao Sneaker', 750000.00, 30, 'sneaker.jpg', 'Chất liệu thoáng khí, êm chân'),
(3, 'Nồi Chiên Không Dầu 5L', 1500000.00, 20, 'noichien.jpg', 'Công nghệ nướng đối lưu giảm mỡ');