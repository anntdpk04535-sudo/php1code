<?php
session_start();
require_once "./db_utils.php";
$db = new DB_UTILS();

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($fullname) || empty($email) || empty($username) || empty($password)) {
        $error = 'Vui lòng điền đầy đủ thông tin!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Định dạng email không hợp lệ!';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải chứa ít nhất 6 ký tự!';
    } else {
        $check = $db->getValue("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($check > 0) {
            $error = 'Tên tài khoản hoặc email đăng ký đã tồn tại!';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (full_name, email, username, password, role) VALUES (?, ?, ?, ?, 'user')";
            if ($db->execute($sql, [$fullname, $email, $username, $hashedPassword])) {
                $success = 'Đăng ký tài khoản thành công! Bạn có thể <a href="login.php">Đăng nhập</a> ngay.';
                $fullname = $email = $username = '';
            } else {
                $error = 'Có lỗi xảy ra từ máy chủ, vui lòng thử lại sau!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng Ký Tài Khoản</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .auth-container {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #1f2937;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #4b5563;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            outline: none;
            font-size: 14px;
        }

        .alert {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
            border: 1px solid transparent;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .btn-auth {
            width: 100%;
            padding: 12px;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-auth:hover {
            background: #059669;
        }

        p.redirect {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            color: #6b7280;
        }

        p.redirect a {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <h2>Đăng Ký Thành Viên</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <form method="POST" action="register.php">
            <div class="form-group">
                <label>Họ và tên</label>
                <input type="text" name="full_name" placeholder="Ví dụ: Nguyễn Văn A"
                    value="<?= htmlspecialchars($fullname ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email liên hệ</label>
                <input type="email" name="email" placeholder="example@gmail.com"
                    value="<?= htmlspecialchars($email ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Tên tài khoản (Username)</label>
                <input type="text" name="username" placeholder="Nhập tên đăng nhập duy nhất"
                    value="<?= htmlspecialchars($username ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu mật an toàn</label>
                <input type="password" name="password" placeholder="Tối thiểu từ 6 ký tự trở lên" required>
            </div>
            <button type="submit" class="btn-auth">Đăng ký ngay</button>
        </form>
        <p class="redirect">Đã có tài khoản thành viên? <a href="login.php">Đăng nhập</a></p>
    </div>
</body>

</html>