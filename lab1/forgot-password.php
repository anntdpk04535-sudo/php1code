<?php
session_start();
$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    if (isset($_SESSION['users'][$email])) {
        $msg = "<span style='color:green';>Một liên kết đặt lại mật khẩu đã được gửi đến email của bạn.</span>";
    } else {
        $msg = "Email không tồn tại trên hệ thống.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Khôi phục mật khẩu - NovaMart</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="brand">NovaMart</div>
        <h2>Khôi phục mật khẩu</h2>
        <p style="color: #666; font-size: 14px; margin-bottom: 20px;">
            Nhập email để nhận liên kết đặt lại mật khẩu.
        </p>
        <?php if($msg) echo "<p class='success'>$msg</p>"; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Nhập email">
            </div>
            <button type="submit">Gửi liên kết</button>
        </form>
        <div class="footer-link">
            <a href="index.php">Quay lại Đăng nhập</a>
        </div>
    </div>
</body>
</html>