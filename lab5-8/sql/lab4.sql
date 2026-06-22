-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 22, 2026 at 04:17 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lab4`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(80) DEFAULT NULL COMMENT 'Font Awesome class, e.g. fa-solid fa-laptop',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `slug`, `icon`, `description`, `created_at`) VALUES
(1, 'Laptop', 'laptop', 'fa-solid fa-laptop', 'Máy tính xách tay các hãng', '2026-06-22 02:16:07'),
(2, 'Điện thoại', 'dien-thoai', 'fa-solid fa-mobile-screen', 'Smartphone chính hãng', '2026-06-22 02:16:07'),
(3, 'Màn hình', 'man-hinh', 'fa-solid fa-desktop', 'Màn hình máy tính', '2026-06-22 02:16:07'),
(4, 'Phụ kiện', 'phu-kien', 'fa-solid fa-plug', 'Cáp, sạc, tai nghe và phụ kiện khác', '2026-06-22 02:16:07'),
(5, 'Thiết bị mạng', 'thiet-bi-mang', 'fa-solid fa-wifi', 'Router, switch, modem', '2026-06-22 02:16:07');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `fullname` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `address` varchar(255) NOT NULL,
  `note` text DEFAULT NULL,
  `payment` varchar(50) NOT NULL,
  `total` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'Đã đặt',
  `cancel_reason` text DEFAULT NULL,
  `created_at` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `fullname`, `phone`, `email`, `address`, `note`, `payment`, `total`, `status`, `cancel_reason`, `created_at`) VALUES
('DH-16753395', 5, 'An', '0123456789', 'abcd@gmail.com', '07 Nguyễn Văn Linh', '', 'vnpay', 250000, 'Hoàn tất', NULL, '2026-06-21 15:56:33'),
('DH-4DF99D84', 5, 'An', '0123456789', 'abcd@gmail.com', '241 hà huy tập', '', 'cod', 250000, 'Hoàn tất', NULL, '2026-06-22 08:49:01'),
('DH-50D96988', 5, 'An', '0123456789', 'abcd@gmail.com', '07 Nguyễn Văn Linh', '', 'vnpay', 250000, 'Đã thanh toán', NULL, '2026-06-21 15:50:26'),
('DH-CBB4B610', 5, 'An', '0323456789', 'abcd@gmail.com', '241 hà huy tập', '', 'cod', 300000, 'Hoàn tất', NULL, '2026-06-22 08:47:47'),
('NM413441', NULL, 'phi', '0337457849', 'vunamphi1202@gmail.com', 'ádawdadwrfe', 'eacdadeada', 'cod', 250000, 'Hoàn tất', NULL, '10/6/2026'),
('NM698164', NULL, 'nguyễn văn a', '0337457849', 'phivnpk04710@gmail.com', 'Buôn Mê Thuật', 'dsadw', 'cod', 250000, 'Hoàn tất', NULL, '7/6/2026');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `product_id` varchar(20) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `description`) VALUES
(4, 'NM698164', '06666', 1, 250000, 'sdvs'),
(5, 'NM413441', '06666', 1, 250000, 'sdvs'),
(34, 'DH-50D96988', '01111', 1, 250000, 'cvsv'),
(36, 'DH-16753395', '05555', 1, 250000, 'mạcna'),
(43, 'DH-CBB4B610', '05555', 1, 300000, 'mạcna'),
(44, 'DH-4DF99D84', '04444', 1, 250000, 'dép lồ');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` varchar(20) NOT NULL,
  `description` text NOT NULL,
  `price` varchar(100) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `category_id` int(11) DEFAULT NULL,
  `image` text NOT NULL
) ;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `description`, `price`, `stock`, `category_id`, `image`) VALUES
('01111', 'Smart Tivi Samsung 4K 65 inch', '20.460.000', 48, NULL, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS-Oe0U7BTdZh7ZhVGSKMxzoL7bFpFpYNXVcg&s'),
('0222', 'Samsung Galaxy S26 ultra', '36.990.000', 30, NULL, 'https://encrypted-tbn0.gstatic.com/shopping?q=tbn:ANd9GcQbSCnHEibp0S8gDBWCkxEuQhyVF46ozQPwld1WEr1XOzsMmXm9F56RPb9wOAYIeety1Q7155WPz5_s8qFpVGqVGQh69BlEoOyuRl0u3I13HCB7VDGV03sk9GxqEyEuWfnksosfGgFYHRI&usqp=CAc'),
('0333', 'iPhone 16', '15.290.000', 33, NULL, 'https://encrypted-tbn2.gstatic.com/shopping?q=tbn:ANd9GcTMS5tqE8_0bbqahxu6Vlat46_IlTBGuNxXgiDrEmaqAX56O4Gc6NxaIkKYRQeM1IC5ByeAL97KKpYLbvi_ydISm1Xq72Sw_Q_9xn-mPRjsMSES73YEXzWXRLIaHRYDZ5vz2NiTMMTgRQ&usqp=CAc'),
('0444', 'Laptop Asus Vivobook 16', '21.390.000', 43, NULL, 'https://cdnv2.tgdd.vn/mwg-static/tgdd/Products/Images/44/334799/asus-vivobook-16-m1607ka-r7-ai-350-mb091ws-638774631402323373-600x600.jpg'),
('04444', 'Samsung Galaxy A50', '1.450.000', 48, NULL, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQiu548I2psCHbdoRxVrsgJvz-A-2r9wXZMtQ&s'),
('05555', 'Laptop Lenovo Ideapad Slim 5 OLED', '35.990.000', 47, NULL, 'https://cdnv2.tgdd.vn/mwg-static/tgdd/Products/Images/44/363882/lenovo-ideapad-slim-5-oled-14agp11-r7-445-83s1003fvn-1-639093377841214065-750x500.jpg'),
('0666', 'Máy tính bảng iPad Air M4 11 inch', '19.290.000', 12, NULL, 'https://cdnv2.tgdd.vn/mwg-static/tgdd/Products/Images/522/363418/ipad-air-m4-11-inch-wifi-xam-0-639176442561577028-750x500.jpg'),
('0777', 'MacBook Pro 14 inch M5', '44.490.000', 21, NULL, 'https://cdnv2.tgdd.vn/mwg-static/tgdd/Products/Images/44/358088/macbook-pro-14-inch-m5-1-638962511461538555-750x500.jpg'),
('0888', 'Canon EOS R50 BODY ONLY - NK', '15.540.000', 9, NULL, 'https://mac24h.vn/images/thumbnails/550/450/detailed/97/r50_v%C3%A0_kit_0bze-d9.jpg'),
('1234', 'Máy ảnh Fujifilm InStax MiNi', '2.390.000', 18, NULL, 'https://cdnv2.tgdd.vn/mwg-static/tgdd/Products/Images/13958/359466/may-chup-anh-lay-lien-fujifilm-instax-mini-12-xanh-mint-1-638993316754098288-750x500.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `status` varchar(50) DEFAULT 'Chờ xử lý',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL COMMENT 'Họ và tên người dùng',
  `email` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Mật khẩu đã mã hóa bằng password_hash',
  `role` varchar(20) NOT NULL DEFAULT 'user' COMMENT 'Vai trò: admin hoặc user',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `username`, `password`, `role`, `reset_token`, `reset_expires`, `created_at`) VALUES
(1, 'vunamphi', 'vunamphi1202@gmail.com', 'phi', '$2y$10$LA7k985/DUG5yGFqPGX9/uDuXCSmLyAYUYoU2eyNmlOqNhke4DEnK', 'admin', NULL, NULL, '2026-06-10 17:33:48'),
(3, 'Phi', 'phivnpk04710@gmail.com', 'vunamphi', '$2y$10$4QmrhKPkewZK/CzaDaQZcuPrlAO8TVy5GmxEgQ/cVzhd.aN04aw9O', 'user', NULL, NULL, '2026-06-12 02:07:43'),
(4, 'An', 'ntda@gmail.com', 'ntda', '$2y$10$56P5CYMxfg9341QDGU/4ae2vJHXo7ly35TrakW5al23IXOa35c/UO', 'admin', NULL, NULL, '2026-06-17 14:41:20'),
(5, 'An', 'abcd@gmail.com', 'dinhan', '$2y$10$jtLBX5.Vee/9DCPLcQIPJeKlV8MmXVZDkzVpoDnjyv3trraJAB5Qi', 'user', NULL, NULL, '2026-06-21 15:38:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `fk_products_category` (`category_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
