<?php
session_start();
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['current_user']); 
}

$error = "";
$is_logged_in = isset($_SESSION['current_user']); 

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$is_logged_in) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Vui lòng nhập tài khoản và mật khẩu.";
    } else {
        if (isset($_SESSION['users'][$email]) && password_verify($password, $_SESSION['users'][$email]['password'])) {
            $_SESSION['current_user'] = $_SESSION['users'][$email]['fullname'];
            $is_logged_in = true;
        } else {
            $error = "Tài khoản hoặc mật khẩu không chính xác.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập - NovaMart</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="brand">NovaMart</div>

        <?php if ($is_logged_in): ?>
            <div class="login-success-card">
                <div style="font-size: 50px; margin-bottom: 10px;">✅</div>
                <h3>Đăng nhập thành công!</h3>
                <p>Chào mừng <strong><?php echo htmlspecialchars($_SESSION['current_user']); ?></strong>.</p>
                
                <a href="index.php?action=logout" style="text-decoration: none;">
                    <button type="button" style="background-color: #6c757d;">Đăng xuất</button>
                </a>
            </div>
        <?php else: ?>
            <h2>Đăng nhập</h2>
            <?php if($error) echo "<div class='error'>$error</div>"; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Tài khoản</label>
                    <input type="email" name="email" placeholder="nhập email đăng nhập" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Mật khẩu</label>
                    <input type="password" name="password" placeholder="nhập mật khẩu">
                </div>
                <button type="submit">Đăng nhập</button>
            </form>
            
            <div class="footer-link">
                Chưa có tài khoản? <a href="register.php">Đăng ký</a><br><br>
                <a href="forgot-password.php" style="color: #666; font-size: 12px;">Quên mật khẩu?</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>