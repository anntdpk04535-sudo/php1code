<?php
// Khởi chạy session nếu chưa được bật
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cách 1: Xóa bỏ dữ liệu mảng thông tin user trong Session hiện tại
if (isset($_SESSION['user'])) {
    unset($_SESSION['user']);
}

// Cách 2 (Triệt để hơn): Hủy hoàn toàn toàn bộ Session trên hệ thống
session_destroy();

// Điều hướng người dùng quay trở lại trang đăng nhập sau khi thoát thành công
header("Location: login.php");
exit;
?>