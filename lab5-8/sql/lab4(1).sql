-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 17, 2026 at 08:24 AM
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
('DH-3662C30C', 1, 'vunamphi123', '0337457849', 'vunamphi1202@gmail.com', '324324/MaiChi Tho', '123546', 'momo', 250000, 'Chờ thanh toán', NULL, '2026-06-14 16:35:00'),
('DH-3831E1DA', 1, 'bhv', '01234567', 'vunamphi1202@gmail.com', '07 Nguyễn Văn Linh', '', 'cod', 1000000, 'Hoàn tất', NULL, '2026-06-16 15:20:24'),
('DH-4C972A10', NULL, 'vunamphi', '0337457849', 'vunamphi1202@gmail.com', '324324/MaiChi Tho', 'ádawdawd', 'cod', 250000, 'Hoàn tất', NULL, '2026-06-12 07:03:20'),
('DH-5D45843B', 1, 'anhday71', '0337457849', 'vunamphi1202@gmail.com', '324324/MaiChi Tho', 'ádadwda', 'vnpay', 250000, 'Đã thanh toán', NULL, '2026-06-13 21:48:18'),
('DH-6B5CB878', NULL, 'vunamphi', '0337457849', 'vunamphi1202@gmail.com', '324324/MaiChi Tho', '121313', 'vnpay', 250000, 'Đã thanh toán', NULL, '2026-06-13 21:30:54'),
('DH-734EB481', 1, 'vunamphi', '0323456789', 'vunamphi1202@gmail.com', '07 Nguyễn Văn Linh', '', 'cod', 2500000, 'Hoàn tất', NULL, '2026-06-16 15:18:31'),
('DH-86D3A286', 1, 'vunamphi', '0123456789', 'vunamphi1202@gmail.com', '241 hà huy tập', '', 'cod', 750000, 'Hoàn tất', NULL, '2026-06-16 15:19:36'),
('DH-A0641BF3', 1, 'vunamphi', '0337457849', 'vunamphi1202@gmail.com', '324324/MaiChi Tho', 'adawdae', 'vnpay', 250000, 'Đã thanh toán', NULL, '2026-06-15 02:11:33'),
('DH-AC83240E', NULL, 'vunamphi', '0337457849', 'vunamphi1202@gmail.com', '324324/MaiChi Tho', '123123', 'vnpay', 250000, 'Chờ thanh toán', NULL, '2026-06-13 21:22:21'),
('DH-AF669726', NULL, 'vunamphi', '0337457849', 'vunamphi1202@gmail.com', '324324/MaiChi Tho', '123131', 'vnpay', 750000, 'Chờ thanh toán', NULL, '2026-06-13 16:20:00'),
('DH-BB00D88E', NULL, 'vunamphi', '0337457849', 'vunamphi1202@gmail.com', '324324/MaiChi Tho', '123213', 'vnpay', 250000, 'Thanh toán thất bại', NULL, '2026-06-13 21:33:38'),
('DH-BBBD5D95', NULL, 'vunamphi', '0876386992', 'phivnpk04710@gmail.com', 'adaasdawda', 'sadadaw', 'cod', 250000, 'Hoàn tất', NULL, '2026-06-12 09:55:41'),
('DH-D7684972', NULL, 'vunamphi', '0337457849', 'vunamphi1202@gmail.com', '324324/MaiChi Tho', '231231', 'vnpay', 750000, 'Đã thanh toán', NULL, '2026-06-13 21:34:32'),
('DH-E70830DC', NULL, 'Phi', '0876386992', 'phivnpk04710@gmail.com', 'adaasdawda', 'dădwadwa', 'cod', 750000, 'Hoàn tất', NULL, '2026-06-12 08:06:04'),
('NM413441', NULL, 'phi', '0337457849', 'vunamphi1202@gmail.com', 'ádawdadwrfe', 'eacdadeada', 'cod', 250000, 'Hoàn tất', NULL, '10/6/2026'),
('NM483024', NULL, 'ấdsfad', '0876368992', 'vunamphi1202@gmail.com', 'ggtrdfrdetdr', 'fchvgbhbn', 'cod', 1000000, 'Đã hủy', 'họ không cần nữa', '7/6/2026'),
('NM648468', NULL, 'ádsfsf', '0869734820', 'scdfvgb@gmail.com', 'sdcfvgbhjkhg', 'sdafghjgfd', 'cod', 250000, 'Đã hủy', NULL, '5/6/2026'),
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
(1, 'NM648468', '05555', 1, 250000, 'mạcna'),
(3, 'NM483024', '0999', 4, 250000, 'zxcvcsz'),
(4, 'NM698164', '06666', 1, 250000, 'sdvs'),
(5, 'NM413441', '06666', 1, 250000, 'sdvs'),
(6, 'DH-4C972A10', '06666', 1, 250000, 'sdvs'),
(7, 'DH-E70830DC', '06666', 3, 250000, 'sdvs'),
(8, 'DH-BBBD5D95', '06666', 1, 250000, 'sdvs'),
(9, 'DH-AF669726', '06666', 3, 250000, 'sdvs'),
(10, 'DH-AC83240E', '06666', 1, 250000, 'sdvs'),
(11, 'DH-6B5CB878', '05555', 1, 250000, 'mạcna'),
(12, 'DH-BB00D88E', '06666', 1, 250000, 'sdvs'),
(13, 'DH-D7684972', '06666', 3, 250000, 'sdvs'),
(14, 'DH-5D45843B', '0999', 1, 250000, 'zxcvcsz'),
(15, 'DH-3662C30C', '0999', 1, 250000, 'zxcvcsz'),
(16, 'DH-A0641BF3', '0999', 1, 250000, 'zxcvcsz'),
(17, 'DH-734EB481', '06666', 10, 250000, 'sdvs'),
(18, 'DH-86D3A286', '05555', 3, 250000, 'mạcna'),
(19, 'DH-3831E1DA', '05555', 1, 250000, 'mạcna'),
(20, 'DH-3831E1DA', '01111', 1, 250000, 'cvsv'),
(21, 'DH-3831E1DA', '06666', 2, 250000, 'sdvs');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` varchar(20) NOT NULL,
  `description` text NOT NULL,
  `price` varchar(100) NOT NULL,
  `image` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `description`, `price`, `image`) VALUES
('01111', 'cvsv', '250.000', 'uploads/1781204387_1781204347_1780648669_FOC.jpg'),
('04444', 'ầ', '250.000', 'uploads/1781204376_1781204355_1780648678_ủa.jpg'),
('05555', 'mạcna', '250.000', 'uploads/1781204366_1780648693_file.jpg'),
('06666', 'sdvs', '250.000', 'uploads/1781204355_1780648678_ủa.jpg'),
('0999', 'zxcvcsz', '250.000', 'uploads/1781204347_1780648669_FOC.jpg');

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
(3, 'Phi', 'phivnpk04710@gmail.com', 'vunamphi', '$2y$10$4QmrhKPkewZK/CzaDaQZcuPrlAO8TVy5GmxEgQ/cVzhd.aN04aw9O', 'user', NULL, NULL, '2026-06-12 02:07:43');

--
-- Indexes for dumped tables
--

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
  ADD PRIMARY KEY (`product_id`);

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
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
